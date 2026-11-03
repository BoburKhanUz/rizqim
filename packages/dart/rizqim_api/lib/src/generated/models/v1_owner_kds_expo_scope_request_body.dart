// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'expo_scope.dart';

part 'v1_owner_kds_expo_scope_request_body.g.dart';

@JsonSerializable()
class V1OwnerKdsExpoScopeRequestBody {
  const V1OwnerKdsExpoScopeRequestBody({
    required this.expoScope,
    this.branchId,
  });

  factory V1OwnerKdsExpoScopeRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerKdsExpoScopeRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'expo_scope')
  final ExpoScope expoScope;

  Map<String, Object?> toJson() => _$V1OwnerKdsExpoScopeRequestBodyToJson(this);
}
