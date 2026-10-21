// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tables.dart';

part 'v1_owner_dining_tables_layout_request_body.g.dart';

@JsonSerializable()
class V1OwnerDiningTablesLayoutRequestBody {
  const V1OwnerDiningTablesLayoutRequestBody({
    required this.branchId,
    required this.tables,
  });

  factory V1OwnerDiningTablesLayoutRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerDiningTablesLayoutRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Tables> tables;

  Map<String, Object?> toJson() =>
      _$V1OwnerDiningTablesLayoutRequestBodyToJson(this);
}
