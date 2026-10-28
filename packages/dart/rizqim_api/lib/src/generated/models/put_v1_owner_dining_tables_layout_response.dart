// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data53.dart';

part 'put_v1_owner_dining_tables_layout_response.g.dart';

@JsonSerializable()
class PutV1OwnerDiningTablesLayoutResponse {
  const PutV1OwnerDiningTablesLayoutResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerDiningTablesLayoutResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerDiningTablesLayoutResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data53> data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerDiningTablesLayoutResponseToJson(this);
}
