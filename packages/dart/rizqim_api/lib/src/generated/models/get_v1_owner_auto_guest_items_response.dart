// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data27.dart';

part 'get_v1_owner_auto_guest_items_response.g.dart';

@JsonSerializable()
class GetV1OwnerAutoGuestItemsResponse {
  const GetV1OwnerAutoGuestItemsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerAutoGuestItemsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerAutoGuestItemsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data27> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerAutoGuestItemsResponseToJson(this);
}
