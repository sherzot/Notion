import { NextRequest } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

function backendBaseUrl(): string {
  return process.env.BACKEND_BASE_URL || "http://backend:8000";
}

async function proxy(req: NextRequest, params: { path: string[] }) {
  const incomingUrl = new URL(req.url);
  const path = params.path.join("/");

  const target = new URL(`${backendBaseUrl()}/api/${path}`);
  target.search = incomingUrl.search;

  const headers = new Headers(req.headers);
  headers.delete("host");

  const method = req.method.toUpperCase();
  const body = method === "GET" || method === "HEAD" ? undefined : await req.arrayBuffer();

  const upstream = await fetch(target, {
    method,
    headers,
    body,
    redirect: "manual",
  });

  const resHeaders = new Headers(upstream.headers);
  resHeaders.delete("content-encoding");
  resHeaders.delete("content-length");

  return new Response(upstream.body, {
    status: upstream.status,
    headers: resHeaders,
  });
}

type RouteCtx = { params: Promise<{ path: string[] }> };

async function proxyFromCtx(req: NextRequest, ctx: RouteCtx) {
  const { path } = await ctx.params;
  return proxy(req, { path });
}

export async function GET(req: NextRequest, ctx: RouteCtx) {
  return proxyFromCtx(req, ctx);
}
export async function POST(req: NextRequest, ctx: RouteCtx) {
  return proxyFromCtx(req, ctx);
}
export async function PUT(req: NextRequest, ctx: RouteCtx) {
  return proxyFromCtx(req, ctx);
}
export async function PATCH(req: NextRequest, ctx: RouteCtx) {
  return proxyFromCtx(req, ctx);
}
export async function DELETE(req: NextRequest, ctx: RouteCtx) {
  return proxyFromCtx(req, ctx);
}
export async function OPTIONS(req: NextRequest, ctx: RouteCtx) {
  return proxyFromCtx(req, ctx);
}

