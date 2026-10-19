/**
 * CodeMirror pieces for the GeoJSON panel. Loaded on demand the first time the panel opens,
 * so entry pages don't download the editor unless it's used.
 */
export { EditorView, basicSetup } from "codemirror";
export { highlightActiveLine, highlightSpecialChars, placeholder } from "@codemirror/view";
export { bracketMatching } from "@codemirror/language";
export { closeBrackets } from "@codemirror/autocomplete";
export { json } from "@codemirror/lang-json";
export { setDiagnostics } from "@codemirror/lint";
