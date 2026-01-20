import { describe, expect, it, vi } from "vitest";
import { createLocation } from "@/test/mocks/data";
import { render, screen } from "@/test/utils";
import { LocationTabs } from "./LocationTabs";

describe("LocationTabs", () => {
  const fridge = createLocation({ id: "fridge-1", name: "Холодильник" });
  const pantry = createLocation({ id: "pantry-1", name: "Кладовка" });

  it("renders 'Все' tab with total count", () => {
    render(
      <LocationTabs
        locations={[]}
        selectedLocationId={null}
        onSelect={vi.fn()}
        counts={{}}
        totalCount={42}
      />,
    );

    expect(screen.getByRole("button", { name: /Все/i })).toBeInTheDocument();
    expect(screen.getByText("42")).toBeInTheDocument();
  });

  it("renders location tabs with counts", () => {
    render(
      <LocationTabs
        locations={[fridge, pantry]}
        selectedLocationId={null}
        onSelect={vi.fn()}
        counts={{ "fridge-1": 10, "pantry-1": 5 }}
        totalCount={15}
      />,
    );

    expect(
      screen.getByRole("button", { name: /Холодильник/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Кладовка/i }),
    ).toBeInTheDocument();
    expect(screen.getByText("10")).toBeInTheDocument();
    expect(screen.getByText("5")).toBeInTheDocument();
  });

  it("calls onSelect with null when 'Все' tab clicked", async () => {
    const onSelect = vi.fn();
    const { user } = render(
      <LocationTabs
        locations={[fridge]}
        selectedLocationId="fridge-1"
        onSelect={onSelect}
        counts={{ "fridge-1": 10 }}
        totalCount={10}
      />,
    );

    await user.click(screen.getByRole("button", { name: /Все/i }));

    expect(onSelect).toHaveBeenCalledWith(null);
  });

  it("calls onSelect with location id when location tab clicked", async () => {
    const onSelect = vi.fn();
    const { user } = render(
      <LocationTabs
        locations={[fridge, pantry]}
        selectedLocationId={null}
        onSelect={onSelect}
        counts={{ "fridge-1": 10, "pantry-1": 5 }}
        totalCount={15}
      />,
    );

    await user.click(screen.getByRole("button", { name: /Холодильник/i }));

    expect(onSelect).toHaveBeenCalledWith("fridge-1");
  });

  it("shows 0 count when location has no entries", () => {
    render(
      <LocationTabs
        locations={[fridge]}
        selectedLocationId={null}
        onSelect={vi.fn()}
        counts={{}}
        totalCount={0}
      />,
    );

    // Should show 0 for both total and the location
    const zeros = screen.getAllByText("0");
    expect(zeros).toHaveLength(2);
  });
});
