// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_send_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidSendRequestBody {
  const V1PosOrdersUuidSendRequestBody({required this.version, this.itemIds});

  factory V1PosOrdersUuidSendRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersUuidSendRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'item_ids')
  final List<int>? itemIds;

  Map<String, Object?> toJson() => _$V1PosOrdersUuidSendRequestBodyToJson(this);
}
