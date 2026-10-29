// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_items_item_seat_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidItemsItemSeatRequestBody {
  const V1PosOrdersUuidItemsItemSeatRequestBody({
    required this.version,
    required this.seatNo,
    this.approvalToken,
  });

  factory V1PosOrdersUuidItemsItemSeatRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidItemsItemSeatRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'seat_no')
  final int? seatNo;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidItemsItemSeatRequestBodyToJson(this);
}
