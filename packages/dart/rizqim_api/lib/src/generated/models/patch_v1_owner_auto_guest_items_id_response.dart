// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data51.dart';

part 'patch_v1_owner_auto_guest_items_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerAutoGuestItemsIdResponse {
  const PatchV1OwnerAutoGuestItemsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerAutoGuestItemsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerAutoGuestItemsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data51 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerAutoGuestItemsIdResponseToJson(this);
}
