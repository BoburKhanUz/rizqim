// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data105.dart';

part 'put_v1_owner_kds_expo_scope_response.g.dart';

@JsonSerializable()
class PutV1OwnerKdsExpoScopeResponse {
  const PutV1OwnerKdsExpoScopeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerKdsExpoScopeResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerKdsExpoScopeResponseFromJson(json);

  final bool success;
  final String message;
  final Data105 data;

  Map<String, Object?> toJson() => _$PutV1OwnerKdsExpoScopeResponseToJson(this);
}
