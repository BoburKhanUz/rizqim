// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_items_item_weight_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidItemsItemWeightRequestBody {
  const V1PosOrdersUuidItemsItemWeightRequestBody({
    required this.version,
    required this.quantity,
    this.approvalToken,
  });

  factory V1PosOrdersUuidItemsItemWeightRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidItemsItemWeightRequestBodyFromJson(json);

  final int version;
  final num quantity;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidItemsItemWeightRequestBodyToJson(this);
}
