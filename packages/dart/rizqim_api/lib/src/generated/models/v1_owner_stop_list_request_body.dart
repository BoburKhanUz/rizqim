// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type6.dart';

part 'v1_owner_stop_list_request_body.g.dart';

@JsonSerializable()
class V1OwnerStopListRequestBody {
  const V1OwnerStopListRequestBody({
    required this.type,
    required this.id,
    this.branchId,
    this.reason,
    this.until,
  });

  factory V1OwnerStopListRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerStopListRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Type6 type;
  final int id;
  final String? reason;
  final DateTime? until;

  Map<String, Object?> toJson() => _$V1OwnerStopListRequestBodyToJson(this);
}
