import { api } from "./client";

export interface Currency {
  code: string;
  name: string;
  symbol: string;
  decimals: number;
  is_base: boolean;
  is_active: boolean;
  latest_rate: string | null;
}

export async function listCurrencies() {
  const { data } = await api.get<{ results: Currency[] }>("/currencies/");
  return data.results;
}

export async function addCurrency(input: { code: string; name: string; symbol?: string; decimals?: number }) {
  const { data } = await api.post<Currency>("/currencies/", input);
  return data;
}

export async function setRate(code: string, rate: number, as_of?: string) {
  const { data } = await api.post<{ currency: string; rate: string; as_of: string }>(
    `/currencies/${code}/rates/`,
    { rate, as_of }
  );
  return data;
}

export async function convert(amount: number, from: string, to: string) {
  const { data } = await api.get<{ amount: number; from: string; to: string; result: number }>(
    "/currencies/convert/",
    { params: { amount, from, to } }
  );
  return data;
}
