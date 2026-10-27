// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data50.dart';

part 'get_v1_owner_dining_tables_response.g.dart';

@JsonSerializable()
class GetV1OwnerDiningTablesResponse {
  const GetV1OwnerDiningTablesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerDiningTablesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerDiningTablesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data50> data;

  Map<String, Object?> toJson() => _$GetV1OwnerDiningTablesResponseToJson(this);
}
