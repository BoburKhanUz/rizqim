// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers4.dart';

part 'v1_pos_bookings_uuid_items_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidItemsRequestBody {
  const V1PosBookingsUuidItemsRequestBody({
    required this.quantity,
    this.productVariantId,
    this.productVariantUuid,
    this.modifiers,
    this.note,
    this.dueAt,
    this.version,
  });

  factory V1PosBookingsUuidItemsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidItemsRequestBodyFromJson(json);

  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;

  /// P3-26: the variant's uuid, as the POS order lines take it (variant_id)
  @JsonKey(name: 'product_variant_uuid')
  final String? productVariantUuid;
  final num quantity;
  final List<Modifiers4>? modifiers;
  final String? note;
  @JsonKey(name: 'due_at')
  final DateTime? dueAt;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidItemsRequestBodyToJson(this);
}
