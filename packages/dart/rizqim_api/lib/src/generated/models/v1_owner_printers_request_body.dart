// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_printers_request_body.g.dart';

@JsonSerializable()
class V1OwnerPrintersRequestBody {
  const V1OwnerPrintersRequestBody({this.branchId});

  factory V1OwnerPrintersRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPrintersRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$V1OwnerPrintersRequestBodyToJson(this);
}
