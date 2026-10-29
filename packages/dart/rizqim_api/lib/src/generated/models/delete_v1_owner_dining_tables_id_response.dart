// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data63.dart';

part 'delete_v1_owner_dining_tables_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerDiningTablesIdResponse {
  const DeleteV1OwnerDiningTablesIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerDiningTablesIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerDiningTablesIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data63 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerDiningTablesIdResponseToJson(this);
}
