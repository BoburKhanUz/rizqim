// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data29.dart';

part 'delete_v1_owner_auto_guest_items_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerAutoGuestItemsIdResponse {
  const DeleteV1OwnerAutoGuestItemsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerAutoGuestItemsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerAutoGuestItemsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data29 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerAutoGuestItemsIdResponseToJson(this);
}
