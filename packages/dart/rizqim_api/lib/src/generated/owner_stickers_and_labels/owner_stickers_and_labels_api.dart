// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_branches_id_label_settings_response.dart';
import '../models/put_v1_owner_branches_id_label_settings_response.dart';
import '../models/v1_owner_branches_id_label_settings_request_body.dart';

part 'owner_stickers_and_labels_api.g.dart';

@RestApi()
abstract class OwnerStickersAndLabelsApi {
  factory OwnerStickersAndLabelsApi(Dio dio, {String? baseUrl}) =
      _OwnerStickersAndLabelsApi;

  /// The branch's sticker and label settings.
  ///
  /// Every key with its default when nothing was saved (`configured: false`): `size` (a LabelSize value, default.
  /// 40x30; `width_mm` and `height_mm` follow), `scope` (a LabelScope value: all, or only `category_ids`), `per_unit`.
  /// (one sticker per piece, default true) and `bag_label` (a bag label for takeaway orders, default true). The till.
  /// reads the same block from GET /pos/bootstrap `labels`.
  @GET('/v1/owner/branches/{id}/label-settings')
  Future<GetV1OwnerBranchesIdLabelSettingsResponse> branchLabelSettingsShow({
    @Path('id') required int id,
  });

  /// Change the branch's sticker and label settings.
  ///
  /// Send only what changes. `scope` `categories` needs at least one entry in `category_ids` (existing category ids);.
  /// `all` clears them. Audited (settings.label_settings_changed).
  @PUT('/v1/owner/branches/{id}/label-settings')
  Future<PutV1OwnerBranchesIdLabelSettingsResponse> branchLabelSettingsUpdate({
    @Path('id') required int id,
    @Body() V1OwnerBranchesIdLabelSettingsRequestBody? body,
  });
}
