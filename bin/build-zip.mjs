// Builds build/huella-verifactu.zip from the committed HEAD with `git archive`: exactly what
// is in git, without the files marked export-ignore in .gitattributes (tests, dev tooling).
// Run it with `npm run build` after committing.
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

mkdirSync("build", { recursive: true });
execFileSync("git", ["archive", "--format=zip", "--prefix=huella-verifactu/", "-o", "build/huella-verifactu.zip", "HEAD"], { stdio: "inherit" });
console.log("build/huella-verifactu.zip");
