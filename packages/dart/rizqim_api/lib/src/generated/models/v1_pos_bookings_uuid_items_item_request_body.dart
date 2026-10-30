// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers8.dart';

part 'v1_pos_bookings_uuid_items_item_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidItemsItemRequestBody {
  const V1PosBookingsUuidItemsItemRequestBody({
    this.productVariantId,
    this.productVariantUuid,
    this.quantity,
    this.modifiers,
    this.note,
    this.dueAt,
    this.version,
  });

  factory V1PosBookingsUuidItemsItemRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidItemsItemRequestBodyFromJson(json);

  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'product_variant_uuid')
  final String? productVariantUuid;

  /// P3-26
  final num? quantity;
  final List<Modifiers8>? modifiers;
  final String? note;
  @JsonKey(name: 'due_at')
  final DateTime? dueAt;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidItemsItemRequestBodyToJson(this);
}
