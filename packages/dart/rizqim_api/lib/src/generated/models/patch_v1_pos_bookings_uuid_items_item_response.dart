// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data156.dart';

part 'patch_v1_pos_bookings_uuid_items_item_response.g.dart';

@JsonSerializable()
class PatchV1PosBookingsUuidItemsItemResponse {
  const PatchV1PosBookingsUuidItemsItemResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1PosBookingsUuidItemsItemResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1PosBookingsUuidItemsItemResponseFromJson(json);

  final bool success;
  final String message;
  final Data156 data;

  Map<String, Object?> toJson() =>
      _$PatchV1PosBookingsUuidItemsItemResponseToJson(this);
}
