import { useQuery } from "@tanstack/react-query";
import { api } from "@/api/client";
import { Badge } from "@/components/ui/badge";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import type { Paginated, User } from "@/types";

export default function UsersPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ["users"],
    queryFn: async () =>
      (await api.get<Paginated<User>>("/users/")).data,
  });

  if (isLoading) return <p className="text-slate-400">Loading users…</p>;
  if (error) return <p className="text-red-400">Failed to load users.</p>;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Users</h1>
      <Table>
        <THead>
          <tr>
            <Th>Email</Th>
            <Th>Name</Th>
            <Th>Role</Th>
            <Th>Status</Th>
          </tr>
        </THead>
        <TBody>
          {data!.results.map((u) => (
            <tr key={u.id}>
              <Td>{u.email}</Td>
              <Td>
                {u.first_name} {u.last_name}
              </Td>
              <Td>
                <Badge tone={u.role}>{u.role}</Badge>
              </Td>
              <Td>
                <Badge tone={u.is_active ? "green" : "red"}>
                  {u.is_active ? "active" : "inactive"}
                </Badge>
              </Td>
            </tr>
          ))}
        </TBody>
      </Table>
    </div>
  );
}
