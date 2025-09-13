import React, { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import {
  BrowserRouter,
  Link,
  NavLink,
  Route,
  Routes,
  useNavigate,
  useParams,
} from "react-router-dom";
import "./styles.css";

/**********************
 * Config & Utilities *
 **********************/
const API_BASE = import.meta.env.VITE_API_BASE || "http://localhost/api";

function getToken() {
  return localStorage.getItem("ah_token") || "";
}
function setToken(t: string) {
  localStorage.setItem("ah_token", t);
}
function clearToken() {
  localStorage.removeItem("ah_token");
}

async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const url = path.startsWith("/") ? `${API_BASE}${path}` : `${API_BASE}/${path}`;
  const headers = new Headers(init.headers);
  if (!headers.has("Content-Type") && init.body && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  const token = getToken();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  const res = await fetch(url, { ...init, headers, credentials: "include" });
  if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
  if (res.status === 204) return undefined as unknown as T;
  return res.json();
}

/**********************
 * Types
 **********************/
type ArtworkListItem = { artwork_id: number; thumbnail_url: string };
type ArtworkDetail = { artwork_id: number; artwork_title: string; description: string; image_url: string; created_at: string };
type UserInfo = { user_id: number; user_name: string; icon_image_url: string; bio?: string };

/**********************
 * API Wrappers
 **********************/
const Api = {
  async listLatest(): Promise<{ artwork_info: ArtworkListItem[] }> {
    return api(`/artwork_info?limit=20`);
  },
  async getArtworkDetail(id: number): Promise<{ artwork_info: ArtworkDetail[]; user_info: UserInfo[] }> {
    return api(`/artworks/${id}`);
  },
  async login(email: string, password: string): Promise<{ status: "success"; token?: string }> {
    return api(`/login`, { method: "POST", body: JSON.stringify({ email, password }) });
  },
};

/**********************
 * Shared UI atoms
 **********************/
type WithChildren = { children?: React.ReactNode };

function Page(
  { title, right, children }: { title: string; right?: React.ReactNode } & WithChildren
) {
  const nav = useNavigate();
  return (
    <>
      <header className="app-header">
        <div className="app-header__inner flex items-center justify-between p-3 border-b">
          <Link to="/" className="brand flex items-center gap-2">
            <img src="/brand/01-logo.svg" alt="足跡百景" className="brand-logo h-8" />
            足跡百景
          </Link>
          <nav className="flex gap-3 text-sm">
            <NavLink to="/templates">型選び</NavLink>
            <NavLink to="/draw">描く</NavLink>
            <NavLink to="/me">マイ</NavLink>
            <AuthBadge />
          </nav>
        </div>
      </header>
      <main className="app-main p-4">
        <div className="page-head flex items-center justify-between mb-4">
          <h1 className="text-xl font-semibold">{title}</h1>
          {right}
        </div>
        {children}
      </main>
      <footer className="bottom-tabs fixed bottom-0 left-0 right-0 border-t bg-white flex justify-around p-2">
        <NavLink to="/" className="tab"><span className="icon icon--home"></span>ホーム</NavLink>
        <NavLink to="/templates" className="tab"><span className="icon icon--template"></span>型</NavLink>
        <NavLink to="/draw" className="tab"><span className="icon icon--draw"></span>描く</NavLink>
        <NavLink to="/me" className="tab"><span className="icon icon--user"></span>マイ</NavLink>
      </footer>
    </>
  );
}

function Card({ className = "", children }: { className?: string } & WithChildren) {
  return <div className={`rounded-xl border bg-white p-4 shadow-sm ${className}`}>{children}</div>;
}

function Button(props: React.ButtonHTMLAttributes<HTMLButtonElement> & { className?: string }) {
  const { className = "", ...rest } = props;
  return <button {...rest} className={`px-4 py-2 rounded-xl border ${className}`} />;
}

function AuthBadge() {
  const nav = useNavigate();
  const token = getToken();
  if (token) {
    return (
      <Button onClick={() => { clearToken(); nav(0); }} className="text-xs">
        ログアウト
      </Button>
    );
  }
  return <Link to="/login" className="text-sm underline">ログイン</Link>;
}

/**********************
 * Pages
 **********************/
function LatestListPage() {
  const [items, setItems] = useState<ArtworkListItem[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  useEffect(() => {
    Api.listLatest().then(r => setItems(r.artwork_info)).catch(e => setErr(e.message));
  }, []);
  return (
    <Page title="最新の足跡百景">
      {err && <Card>{err}</Card>}
      {!items && <Card>読み込み中…</Card>}
      {items && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {items.map((a) => (
            <Link key={a.artwork_id} to={`/artworks/${a.artwork_id}`}>
              <Card>
                <img src={a.thumbnail_url} alt="" className="rounded-md" />
              </Card>
            </Link>
          ))}
        </div>
      )}
    </Page>
  );
}

function ArtworkDetailPage() {
  const { id } = useParams();
  const [detail, setDetail] = useState<ArtworkDetail | null>(null);
  const [author, setAuthor] = useState<UserInfo | null>(null);
  useEffect(() => {
    if (!id) return;
    Api.getArtworkDetail(Number(id)).then((r) => {
      setDetail(r.artwork_info[0]);
      setAuthor(r.user_info[0]);
    });
  }, [id]);
  return (
    <Page title={`作品 #${id}`}>
      {!detail && <Card>読み込み中…</Card>}
      {detail && (
        <Card>
          <img src={detail.image_url} alt="" className="rounded-md" />
          <h2 className="font-bold mt-2">{detail.artwork_title}</h2>
          <p className="mt-2">{detail.description}</p>
          <p className="text-xs text-slate-500">{detail.created_at}</p>
          {author && <p className="mt-2">作者: {author.user_name}</p>}
        </Card>
      )}
    </Page>
  );
}

function LoginPage() {
  const nav = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const r = await Api.login(email, password);
    if (r.token) {
      setToken(r.token);
      nav("/me");
    }
  };
  return (
    <Page title="ログイン">
      <Card>
        <form onSubmit={onSubmit} className="grid gap-2">
          <input type="email" placeholder="メール" value={email} onChange={(e) => setEmail(e.target.value)} className="border p-2 rounded" />
          <input type="password" placeholder="パスワード" value={password} onChange={(e) => setPassword(e.target.value)} className="border p-2 rounded" />
          <Button className="bg-slate-900 text-white">ログイン</Button>
        </form>
      </Card>
    </Page>
  );
}

/**********************
 * Router
 **********************/
function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<LatestListPage />} />
        <Route path="/artworks/:id" element={<ArtworkDetailPage />} />
        <Route path="/login" element={<LoginPage />} />
      </Routes>
    </BrowserRouter>
  );
}

createRoot(document.getElementById("root")!).render(<App />);
