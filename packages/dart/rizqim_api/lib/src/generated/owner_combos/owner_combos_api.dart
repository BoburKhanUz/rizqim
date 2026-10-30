// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_combos_id_response.dart';
import '../models/get_v1_owner_combos_response.dart';
import '../models/post_v1_owner_combos_id_preview_response.dart';
import '../models/post_v1_owner_combos_response.dart';
import '../models/put_v1_owner_combos_id_response.dart';
import '../models/v1_owner_combos_id_preview_request_body.dart';

part 'owner_combos_api.g.dart';

@RestApi()
abstract class OwnerCombosApi {
  factory OwnerCombosApi(Dio dio, {String? baseUrl}) = _OwnerCombosApi;

  /// Combo list.
  ///
  /// P6-31, module `combo`: the store's combos. A combo is a normal menu product (its price, MXIK code, channel prices.
  /// and daily menu are set in the menu like any dish's) whose buyer picks its parts in ordered slots. menu.view or.
  /// menu.manage.
  @GET('/v1/owner/combos')
  Future<GetV1OwnerCombosResponse> comboIndex();

  /// Make a product a combo.
  ///
  /// `product_id`: a product of the store sold by the piece (its price is the combo's base price); `slots` in order,.
  /// each `name` ("Asosiy", "Ichimlik", "Sous"), `min_picks` (0: optional; default 1), `max_picks` (1–10, default.
  /// max(1, min)), `allow_modifiers` (the buyer may change the part's own modifiers; their added deltas are charged).
  /// and 1–20 `options`: `variant_id` (a dish sold by the piece), `quantity` (pieces, default 1), `price_delta` (0 or.
  /// more, added to the combo's price), `modifiers` (a preset: [{modifier_id, quantity?}] of the dish's own modifiers,.
  /// included in the option's price), `is_default` (one per slot: taken when the slot is left out) and `label` (the.
  /// receipt text, e.g. "Ichimlik — 0,4 l Pepsi"; default "<slot> — <dish> (<variant>), <modifiers>"). Up to 8 slots.
  /// A part cannot be the combo itself, another combo or a kompleks; a kompleks product cannot be a combo. Saving a.
  /// product that already is a combo replaces its slots. Sold lines keep the parts they had (`combo_parts`). Audited.
  /// (`menu.combo_saved`).
  @POST('/v1/owner/combos')
  Future<PostV1OwnerCombosResponse> comboStore();

  /// Change a combo.
  ///
  /// Replaces the slots (sent in full) and/or switches the combo off (`is_active` false: it is sold as a plain product).
  @PUT('/v1/owner/combos/{id}')
  Future<PutV1OwnerCombosIdResponse> comboUpdate({@Path('id') required int id});

  /// Delete a combo.
  ///
  /// The product stays on the menu as a plain dish; lines already sold keep their parts. Audited (`menu.combo_deleted`).
  @DELETE('/v1/owner/combos/{id}')
  Future<DeleteV1OwnerCombosIdResponse> comboDestroy({
    @Path('id') required int id,
  });

  /// Receipt preview of a combo.
  ///
  /// What the guest's receipt and the fiscal receipt show for a pick (`picks` as on an order line: [{option_id,.
  /// quantity?, modifiers?}], left out: every slot's default) on a channel (`channel`, default `pos_takeaway`): the.
  /// ONE combo line (name, quantity 1, price = base + deltas), its parts without prices, and the fiscal lines in both.
  /// modes (`single`: the combo with its own MXIK; `split`: one line per part, the shares adding up to the price).
  /// Availability is not asked (it is a preview). 422 like an order line's pick (`combo_slot_required`...).
  @POST('/v1/owner/combos/{id}/preview')
  Future<PostV1OwnerCombosIdPreviewResponse> comboPreview({
    @Path('id') required int id,
    @Body() V1OwnerCombosIdPreviewRequestBody? body,
  });
}
