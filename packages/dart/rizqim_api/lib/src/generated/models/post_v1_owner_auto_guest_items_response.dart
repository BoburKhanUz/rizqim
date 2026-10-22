// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data28.dart';

part 'post_v1_owner_auto_guest_items_response.g.dart';

@JsonSerializable()
class PostV1OwnerAutoGuestItemsResponse {
  const PostV1OwnerAutoGuestItemsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerAutoGuestItemsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerAutoGuestItemsResponseFromJson(json);

  final bool success;
  final String message;
  final Data28 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerAutoGuestItemsResponseToJson(this);
}
