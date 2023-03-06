import { AttacksActionType, AttacksState } from "../actions/types/attacksTypes"
import { 
    FETCH_GET_ATTACKS_REQUEST, 
    FETCH_GET_ATTACKS_SUCCESS, 
    FETCH_GET_ATTACKS_FAILURE 
} from '../actions/enums/attacksTypes';

const initialState: AttacksState = {
    pending: false,
    attacks: { entities: [], totalCount: 0 },
    error: null
}

export const attacksReducer = (state: AttacksState = initialState, action: AttacksActionType) => {
    switch (action.type) {
        case FETCH_GET_ATTACKS_REQUEST:
            return { pending: true } 
        case FETCH_GET_ATTACKS_SUCCESS:
            return { pending: false, attacks: action.payload.attacks }
        case FETCH_GET_ATTACKS_FAILURE:
            return { pending: false, error: action.payload.error }
        default:
            return state;
    }    
}
