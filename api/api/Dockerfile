FROM golang:1.22-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN go build -o api main.go
FROM alpine:3.20

WORKDIR /app

RUN apk add --no-cache ca-certificates

COPY --from=builder /app/api /app/api
COPY --from=builder /app/uploads /app/uploads

RUN mkdir -p /app/uploads

EXPOSE 8080

CMD ["/app/api"]