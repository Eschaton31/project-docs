import { format } from 'date-fns'

/** 'oh-pipeline-tech-manual_{yyyyMMdd}' → 'oh-pipeline-tech-manual_20261021' (any date-fns pattern in braces). */
export function printFileName(pattern, date = new Date()) {
  return pattern.replace(/\{([^}]+)}/g, (_, token) => format(date, token))
}
