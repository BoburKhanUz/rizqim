// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'guest_items.g.dart';

@JsonSerializable()
class GuestItems {
  const GuestItems({
    required this.hallId,
    required this.variantUuid,
    required this.quantityPerGuest,
    required this.sortOrder,
  });

  factory GuestItems.fromJson(Map<String, Object?> json) =>
      _$GuestItemsFromJson(json);

  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'variant_uuid')
  final String variantUuid;
  @JsonKey(name: 'quantity_per_guest')
  final String quantityPerGuest;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$GuestItemsToJson(this);
}
