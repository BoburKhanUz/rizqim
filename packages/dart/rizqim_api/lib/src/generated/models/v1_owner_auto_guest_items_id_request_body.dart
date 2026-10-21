// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_auto_guest_items_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerAutoGuestItemsIdRequestBody {
  const V1OwnerAutoGuestItemsIdRequestBody({
    this.quantityPerGuest,
    this.isActive,
    this.sortOrder,
  });

  factory V1OwnerAutoGuestItemsIdRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerAutoGuestItemsIdRequestBodyFromJson(json);

  @JsonKey(name: 'quantity_per_guest')
  final num? quantityPerGuest;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() =>
      _$V1OwnerAutoGuestItemsIdRequestBodyToJson(this);
}
