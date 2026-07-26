import RecipeFormPage, {
    type EditableRecipe,
    type FoodOption,
} from './create';

export default function RecipeEdit({
    recipe,
    createdFood,
}: {
    recipe: EditableRecipe;
    createdFood: FoodOption | null;
}) {
    return (
        <RecipeFormPage recipe={recipe} createdFood={createdFood} />
    );
}
