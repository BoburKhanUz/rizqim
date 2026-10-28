// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data44.dart';

part 'post_v1_owner_delivery_zones_response.g.dart';

@JsonSerializable()
class PostV1OwnerDeliveryZonesResponse {
  const PostV1OwnerDeliveryZonesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerDeliveryZonesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerDeliveryZonesResponseFromJson(json);

  final bool success;
  final String message;
  final Data44 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerDeliveryZonesResponseToJson(this);
}
