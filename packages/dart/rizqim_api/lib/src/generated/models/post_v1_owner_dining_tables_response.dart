// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data63.dart';

part 'post_v1_owner_dining_tables_response.g.dart';

@JsonSerializable()
class PostV1OwnerDiningTablesResponse {
  const PostV1OwnerDiningTablesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerDiningTablesResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerDiningTablesResponseFromJson(json);

  final bool success;
  final String message;
  final Data63 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerDiningTablesResponseToJson(this);
}
