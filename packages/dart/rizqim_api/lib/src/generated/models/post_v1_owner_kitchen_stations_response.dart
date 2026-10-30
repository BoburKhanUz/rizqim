// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data110.dart';

part 'post_v1_owner_kitchen_stations_response.g.dart';

@JsonSerializable()
class PostV1OwnerKitchenStationsResponse {
  const PostV1OwnerKitchenStationsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerKitchenStationsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerKitchenStationsResponseFromJson(json);

  final bool success;
  final String message;
  final Data110 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerKitchenStationsResponseToJson(this);
}
