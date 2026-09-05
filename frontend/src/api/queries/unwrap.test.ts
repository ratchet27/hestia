import { describe, expect, it } from "vitest";
import { ApiError } from "../client";
import { unwrap } from "./unwrap";

describe("unwrap", () => {
  it("returns the inner payload of a success envelope", () => {
    const response = {
      status: 200 as const,
      data: { data: { id: "t1" } },
      headers: new Headers(),
    };
    expect(unwrap(response)).toEqual({ id: "t1" });
  });

  it("throws ApiError when the envelope carries no payload", () => {
    const response = {
      status: 200 as const,
      data: { data: undefined },
      headers: new Headers(),
    };
    expect(() => unwrap(response)).toThrow(ApiError);
  });
});
