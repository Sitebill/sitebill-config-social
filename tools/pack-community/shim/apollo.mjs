// Замена `import { gql } from '@apollo/client/core'`: в приложении документы — просто строки, их разбирает host.gql.
export const gql = (strings, ...values) => strings.reduce((acc, s, i) => acc + s + (i < values.length ? values[i] : ''), '')
