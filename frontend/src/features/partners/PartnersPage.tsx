import { useMemo, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Pencil, Plus, Trash2, Truck, UserSquare2 } from "lucide-react";
import { partnersApi } from "@/api/partners";
import { Badge } from "@/components/ui/badge";
import { Tooltip } from "@/components/ui/tooltip";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";
import { useI18n } from "@/lib/i18n";
import type { Partner } from "@/types";

const empty = { name: "", email: "", phone: "", address: "", notes: "" };

export default function PartnersPage({
  kind,
}: {
  kind: "customers" | "suppliers";
  title?: string;
}) {
  const { user } = useAuth();
  const { t } = useI18n();
  const isEmployee = user!.role === "employee";
  // Employees may create customers but not edit; suppliers are read-only for them.
  const canCreate = kind === "customers" || !isEmployee;
  const canModify = !isEmployee;

  const client = useMemo(() => partnersApi(kind), [kind]);
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [dialog, setDialog] = useState<"create" | Partner | null>(null);
  const [form, setForm] = useState(empty);
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: [kind, search],
    queryFn: () => client.list(search ? { search } : {}),
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: [kind] });

  const saveMutation = useMutation({
    mutationFn: () =>
      dialog === "create"
        ? client.create(form)
        : client.update((dialog as Partner).id, form),
    onSuccess: () => {
      invalidate();
      setDialog(null);
    },
    onError: (err: any) => {
      const data = err?.response?.data;
      setError(data ? Object.values(data).flat().join(" ") : t("common.requestFailed"));
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: client.deactivate,
    onSuccess: invalidate,
  });

  function openDialog(target: "create" | Partner) {
    setError("");
    setForm(
      target === "create"
        ? empty
        : {
            name: target.name,
            email: target.email,
            phone: target.phone,
            address: target.address,
            notes: target.notes,
          }
    );
    setDialog(target);
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    saveMutation.mutate();
  }

  const set = (k: keyof typeof empty) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div />
        {canCreate && (
          <Button onClick={() => openDialog("create")}>
            <Plus className="h-4 w-4" /> {t(`partners.new.${kind}`)}
          </Button>
        )}
      </div>

      <Input
        placeholder={t("partners.search")}
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="max-w-xs"
      />

      {isLoading ? (
        <TableSkeleton rows={5} />
      ) : data!.results.length === 0 ? (
        <EmptyState
          icon={kind === "customers" ? UserSquare2 : Truck}
          title={search ? t("partners.noMatches") : t(`partners.empty.${kind}`)}
          hint={search ? t("partners.searchHint") : t(`partners.emptyHint.${kind}`)}
          action={
            canCreate && !search ? (
              <Button onClick={() => openDialog("create")}>
                <Plus className="h-4 w-4" /> {t(`partners.new.${kind}`)}
              </Button>
            ) : undefined
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("field.name")}</Th>
              <Th>{t("field.email")}</Th>
              <Th>{t("field.phone")}</Th>
              <Th>{t("common.status")}</Th>
              {canModify && <Th />}
            </tr>
          </THead>
          <TBody>
            {data!.results.map((p) => (
              <tr key={p.id} className={!p.is_active ? "opacity-50" : undefined}>
                <Td>{p.name}</Td>
                <Td className="text-text-2">{p.email || "-"}</Td>
                <Td className="text-text-2">{p.phone || "-"}</Td>
                <Td>
                  <Badge tone={p.is_active ? "green" : "red"}>
                    {p.is_active ? t("common.active") : t("common.inactive")}
                  </Badge>
                </Td>
                {canModify && (
                  <Td className="text-right">
                    <Tooltip label={t("partners.editDetails")}>
                      <Button variant="ghost" size="icon" aria-label={t("common.edit")} onClick={() => openDialog(p)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    </Tooltip>
                    {p.is_active && (
                      <Tooltip label={t("partners.hideContact")}>
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label={t("common.deactivate")}
                          onClick={() => deactivateMutation.mutate(p.id)}
                        >
                          <Trash2 className="h-4 w-4 text-danger" />
                        </Button>
                      </Tooltip>
                    )}
                  </Td>
                )}
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog
        open={dialog !== null}
        onClose={() => setDialog(null)}
        title={dialog === "create" ? t(`partners.new.${kind}`) : t(`partners.edit.${kind}`)}
      >
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="p-name">{t("field.name")}</Label>
            <Input id="p-name" value={form.name} onChange={set("name")} required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="p-email">{t("field.email")}</Label>
              <Input id="p-email" type="email" value={form.email} onChange={set("email")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-phone">{t("field.phone")}</Label>
              <Input id="p-phone" value={form.phone} onChange={set("phone")} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="p-address">{t("field.address")}</Label>
            <Input id="p-address" value={form.address} onChange={set("address")} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="p-notes">{t("field.notes")}</Label>
            <Input id="p-notes" value={form.notes} onChange={set("notes")} />
          </div>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={saveMutation.isPending}>
            {saveMutation.isPending ? t("common.saving") : t("common.save")}
          </Button>
        </form>
      </Dialog>
    </div>
  );
}
