import { useMemo, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { partnersApi } from "@/api/partners";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";
import type { Partner } from "@/types";

const empty = { name: "", email: "", phone: "", address: "", notes: "" };

export default function PartnersPage({
  kind,
  title,
}: {
  kind: "customers" | "suppliers";
  title: string;
}) {
  const { user } = useAuth();
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
      setError(data ? Object.values(data).flat().join(" ") : "Request failed.");
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
        <h1 className="text-2xl font-bold">{title}</h1>
        {canCreate && (
          <Button onClick={() => openDialog("create")}>
            <Plus className="h-4 w-4" /> New {title.slice(0, -1).toLowerCase()}
          </Button>
        )}
      </div>

      <Input
        placeholder="Search by name, email or phone…"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="max-w-xs"
      />

      {isLoading ? (
        <p className="text-slate-400">Loading…</p>
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Name</Th>
              <Th>Email</Th>
              <Th>Phone</Th>
              <Th>Status</Th>
              {canModify && <Th />}
            </tr>
          </THead>
          <TBody>
            {data!.results.map((p) => (
              <tr key={p.id} className={!p.is_active ? "opacity-50" : undefined}>
                <Td>{p.name}</Td>
                <Td className="text-slate-400">{p.email || "—"}</Td>
                <Td className="text-slate-400">{p.phone || "—"}</Td>
                <Td>
                  <Badge tone={p.is_active ? "green" : "red"}>
                    {p.is_active ? "active" : "inactive"}
                  </Badge>
                </Td>
                {canModify && (
                  <Td className="text-right">
                    <Button variant="ghost" size="icon" onClick={() => openDialog(p)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    {p.is_active && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => deactivateMutation.mutate(p.id)}
                      >
                        <Trash2 className="h-4 w-4 text-red-400" />
                      </Button>
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
        title={dialog === "create" ? `New ${title.slice(0, -1).toLowerCase()}` : `Edit ${title.slice(0, -1).toLowerCase()}`}
      >
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="p-name">Name</Label>
            <Input id="p-name" value={form.name} onChange={set("name")} required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="p-email">Email</Label>
              <Input id="p-email" type="email" value={form.email} onChange={set("email")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-phone">Phone</Label>
              <Input id="p-phone" value={form.phone} onChange={set("phone")} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="p-address">Address</Label>
            <Input id="p-address" value={form.address} onChange={set("address")} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="p-notes">Notes</Label>
            <Input id="p-notes" value={form.notes} onChange={set("notes")} />
          </div>
          {error && <p className="text-sm text-red-400">{error}</p>}
          <Button type="submit" className="w-full" disabled={saveMutation.isPending}>
            {saveMutation.isPending ? "Saving…" : "Save"}
          </Button>
        </form>
      </Dialog>
    </div>
  );
}
