// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data191.dart';

part 'post_v1_superadmin_stores_response.g.dart';

@JsonSerializable()
class PostV1SuperadminStoresResponse {
  const PostV1SuperadminStoresResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1SuperadminStoresResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1SuperadminStoresResponseFromJson(json);

  final bool success;
  final String message;
  final Data191 data;

  Map<String, Object?> toJson() => _$PostV1SuperadminStoresResponseToJson(this);
}
