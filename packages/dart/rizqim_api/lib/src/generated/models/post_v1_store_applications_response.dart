// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data215.dart';

part 'post_v1_store_applications_response.g.dart';

@JsonSerializable()
class PostV1StoreApplicationsResponse {
  const PostV1StoreApplicationsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1StoreApplicationsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1StoreApplicationsResponseFromJson(json);

  final bool success;
  final String message;
  final Data215 data;

  Map<String, Object?> toJson() =>
      _$PostV1StoreApplicationsResponseToJson(this);
}
