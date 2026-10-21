// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data174.dart';

part 'post_v1_stores_store_id_rate_response.g.dart';

@JsonSerializable()
class PostV1StoresStoreIdRateResponse {
  const PostV1StoresStoreIdRateResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1StoresStoreIdRateResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1StoresStoreIdRateResponseFromJson(json);

  final bool success;
  final String message;
  final Data174 data;

  Map<String, Object?> toJson() =>
      _$PostV1StoresStoreIdRateResponseToJson(this);
}
