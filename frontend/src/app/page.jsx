"use client";

import { useEffect, useMemo, useState } from "react";

const LS_TOKEN_KEY = "notion_token_v1";

function isRecord(v) {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

function errorMessage(e) {
  if (e instanceof Error) return e.message;
  if (typeof e === "string") return e;
  return "Unknown error";
}

async function apiFetch(path, init = {}) {
  const url = path;
  const headers = {
    "Content-Type": "application/json",
  };
  if (init.headers) new Headers(init.headers).forEach((v, k) => (headers[k] = v));
  if (init.token) headers.Authorization = `Bearer ${init.token}`;

  const res = await fetch(url, { ...init, headers });
  const text = await res.text();
  let data = text;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    // noop
  }
  if (!res.ok) {
    const msg =
      isRecord(data) && "message" in data ? String(data.message) : `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

export default function Home() {
  const [token, setToken] = useState(() => {
    try {
      return localStorage.getItem(LS_TOKEN_KEY) ?? "";
    } catch {
      return "";
    }
  });
  const [user, setUser] = useState(null);
  const [log, setLog] = useState("");

  const [email, setEmail] = useState("dev@example.com");
  const [password, setPassword] = useState("password123");
  const [name, setName] = useState("Dev");

  const [taskTitle, setTaskTitle] = useState("First task");
  const [taskDueAt, setTaskDueAt] = useState("");

  const [noteTitle, setNoteTitle] = useState("Note title");
  const [noteBody, setNoteBody] = useState("");
  const [noteTags, setNoteTags] = useState("work,idea");

  const [eventTitle, setEventTitle] = useState("Meeting");
  const [eventStartAt, setEventStartAt] = useState("");
  const [eventEndAt, setEventEndAt] = useState("");
  const [eventRemind, setEventRemind] = useState(10);

  const [tgType, setTgType] = useState("private");
  const [tgChatId, setTgChatId] = useState("");

  useEffect(() => {
    if (token) localStorage.setItem(LS_TOKEN_KEY, token);
    else localStorage.removeItem(LS_TOKEN_KEY);
  }, [token]);

  const prettyUser = useMemo(() => {
    return user ? JSON.stringify(user, null, 2) : "";
  }, [user]);

  async function handleRegister() {
    try {
      setLog("register...");
      const res = await apiFetch("/api/auth/register", {
        method: "POST",
        body: JSON.stringify({ name, email, password }),
      });
      setToken(res.token);
      setUser(res.user);
      setLog("register: ok");
    } catch (e) {
      setLog(`register: ${errorMessage(e)}`);
    }
  }

  async function handleLogin() {
    try {
      setLog("login...");
      const res = await apiFetch("/api/auth/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      setToken(res.token);
      setUser(res.user);
      setLog("login: ok");
    } catch (e) {
      setLog(`login: ${errorMessage(e)}`);
    }
  }

  async function handleMe() {
    try {
      setLog("me...");
      const res = await apiFetch("/api/auth/me", {
        method: "GET",
        token,
      });
      setUser(res.user);
      setLog("me: ok");
    } catch (e) {
      setLog(`me: ${errorMessage(e)}`);
    }
  }

  async function handleLogout() {
    try {
      setLog("logout...");
      await apiFetch("/api/auth/logout", { method: "POST", token });
      setToken("");
      setUser(null);
      setLog("logout: ok");
    } catch (e) {
      setLog(`logout: ${errorMessage(e)}`);
    }
  }

  async function createTask() {
    try {
      setLog("create task...");
      await apiFetch("/api/tasks", {
        method: "POST",
        token,
        body: JSON.stringify({
          title: taskTitle,
          due_at: taskDueAt || null,
        }),
      });
      setLog("task: created (Telegram enabled bo‘lsa xabar ketadi)");
    } catch (e) {
      setLog(`task: ${errorMessage(e)}`);
    }
  }

  async function createNote() {
    try {
      setLog("create note...");
      await apiFetch("/api/notes", {
        method: "POST",
        token,
        body: JSON.stringify({
          title: noteTitle,
          body: noteBody || null,
          tags: noteTags
            .split(",")
            .map((s) => s.trim())
            .filter(Boolean),
        }),
      });
      setLog("note: created (Telegram enabled bo‘lsa xabar ketadi)");
    } catch (e) {
      setLog(`note: ${errorMessage(e)}`);
    }
  }

  async function createEvent() {
    try {
      setLog("create calendar event...");
      await apiFetch("/api/calendar-events", {
        method: "POST",
        token,
        body: JSON.stringify({
          title: eventTitle,
          start_at: eventStartAt,
          end_at: eventEndAt || null,
          remind_before_minute: Number(eventRemind),
        }),
      });
      setLog("calendar-event: created (Telegram enabled bo‘lsa xabar ketadi)");
    } catch (e) {
      setLog(`calendar-event: ${errorMessage(e)}`);
    }
  }

  async function addTelegramTarget() {
    try {
      setLog("add telegram target...");
      await apiFetch("/api/telegram-targets", {
        method: "POST",
        token,
        body: JSON.stringify({
          type: tgType,
          chat_id: tgChatId,
          enabled: true,
        }),
      });
      setLog("telegram target: saved");
    } catch (e) {
      setLog(`telegram target: ${errorMessage(e)}`);
    }
  }

  return (
    <div className="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-black dark:text-zinc-50">
      <div className="mx-auto max-w-5xl p-6 space-y-6">
        <header className="flex flex-col gap-2">
          <h1 className="text-2xl font-semibold">Notion Mini</h1>
          <p className="text-sm text-zinc-600 dark:text-zinc-400">
            API: <span className="font-mono">/api (proxy)</span>
          </p>
        </header>

        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <section className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950">
            <h2 className="font-semibold">Auth</h2>
            <div className="mt-3 space-y-3">
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input
                  className="rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="name"
                />
                <input
                  className="rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="email"
                />
              </div>
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="password"
              />
              <div className="flex flex-wrap gap-2">
                <button
                  className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-black"
                  onClick={handleRegister}
                >
                  Register
                </button>
                <button
                  className="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-white/10"
                  onClick={handleLogin}
                >
                  Login
                </button>
                <button
                  className="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-white/10"
                  onClick={handleMe}
                  disabled={!token}
                >
                  Me
                </button>
                <button
                  className="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-white/10"
                  onClick={handleLogout}
                  disabled={!token}
                >
                  Logout
                </button>
              </div>

              <div className="text-xs text-zinc-600 dark:text-zinc-400">
                Token:{" "}
                <span className="font-mono">
                  {token ? `${token.slice(0, 8)}…` : "(none)"}
                </span>
              </div>

              {prettyUser ? (
                <pre className="max-h-48 overflow-auto rounded-lg bg-zinc-100 p-3 text-xs dark:bg-black/40">
                  {prettyUser}
                </pre>
              ) : null}
            </div>
          </section>

          <section className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950">
            <h2 className="font-semibold">Telegram target</h2>
            <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              chat_id topish: botga “/start” yozing, keyin webhook log yoki Telegram API orqali.
            </p>
            <div className="mt-3 space-y-3">
              <div className="flex gap-2">
                <select
                  className="rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                  value={tgType}
                  onChange={(e) =>
                    setTgType(e.target.value === "channel" ? "channel" : "private")
                  }
                >
                  <option value="private">private</option>
                  <option value="channel">channel</option>
                </select>
                <input
                  className="flex-1 rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                  value={tgChatId}
                  onChange={(e) => setTgChatId(e.target.value)}
                  placeholder="chat_id (masalan: 123456789 yoki -100...)"
                />
              </div>
              <button
                className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-black"
                onClick={addTelegramTarget}
                disabled={!token}
              >
                Save target
              </button>
            </div>
          </section>
        </div>

        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
          <section className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950">
            <h2 className="font-semibold">Task</h2>
            <div className="mt-3 space-y-3">
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={taskTitle}
                onChange={(e) => setTaskTitle(e.target.value)}
                placeholder="title"
              />
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={taskDueAt}
                onChange={(e) => setTaskDueAt(e.target.value)}
                placeholder="due_at (ISO, optional)"
              />
              <button
                className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-black"
                onClick={createTask}
                disabled={!token}
              >
                Create task
              </button>
            </div>
          </section>

          <section className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950">
            <h2 className="font-semibold">Note</h2>
            <div className="mt-3 space-y-3">
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={noteTitle}
                onChange={(e) => setNoteTitle(e.target.value)}
                placeholder="title"
              />
              <textarea
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={noteBody}
                onChange={(e) => setNoteBody(e.target.value)}
                placeholder="body (optional)"
                rows={3}
              />
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={noteTags}
                onChange={(e) => setNoteTags(e.target.value)}
                placeholder="tags: a,b,c"
              />
              <button
                className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-black"
                onClick={createNote}
                disabled={!token}
              >
                Create note
              </button>
            </div>
          </section>

          <section className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950">
            <h2 className="font-semibold">Calendar event</h2>
            <div className="mt-3 space-y-3">
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={eventTitle}
                onChange={(e) => setEventTitle(e.target.value)}
                placeholder="title"
              />
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={eventStartAt}
                onChange={(e) => setEventStartAt(e.target.value)}
                placeholder="start_at (ISO)"
              />
              <input
                className="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                value={eventEndAt}
                onChange={(e) => setEventEndAt(e.target.value)}
                placeholder="end_at (ISO, optional)"
              />
              <div className="flex items-center gap-2">
                <span className="text-sm text-zinc-600 dark:text-zinc-400">
                  remind:
                </span>
                <input
                  className="w-24 rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm dark:border-white/10"
                  type="number"
                  value={eventRemind}
                  onChange={(e) => setEventRemind(Number(e.target.value))}
                />
                <span className="text-sm text-zinc-600 dark:text-zinc-400">
                  min
                </span>
              </div>
              <button
                className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-black"
                onClick={createEvent}
                disabled={!token}
              >
                Create event
              </button>
            </div>
          </section>
        </div>

        <section className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950">
          <h2 className="font-semibold">Log</h2>
          <pre className="mt-3 min-h-12 whitespace-pre-wrap rounded-lg bg-zinc-100 p-3 text-xs dark:bg-black/40">
            {log || "(no logs)"}
          </pre>
        </section>
      </div>
    </div>
  );
}

