// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_items_item_void_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidItemsItemVoidRequestBody {
  const V1PosOrdersUuidItemsItemVoidRequestBody({
    required this.version,
    this.reason,
    this.approvalToken,
    this.restock,
  });

  factory V1PosOrdersUuidItemsItemVoidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidItemsItemVoidRequestBodyFromJson(json);

  final int version;
  final String? reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;
  final bool? restock;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidItemsItemVoidRequestBodyToJson(this);
}
