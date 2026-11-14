// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_ingredients_id_stop_request_body.g.dart';

@JsonSerializable()
class V1OwnerIngredientsIdStopRequestBody {
  const V1OwnerIngredientsIdStopRequestBody({
    this.branchId,
    this.reason,
    this.until,
  });

  factory V1OwnerIngredientsIdStopRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerIngredientsIdStopRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String? reason;
  final DateTime? until;

  Map<String, Object?> toJson() =>
      _$V1OwnerIngredientsIdStopRequestBodyToJson(this);
}
