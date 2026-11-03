// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_kds_expo_scope_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerKdsExpoScopeRequestBody _$V1OwnerKdsExpoScopeRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerKdsExpoScopeRequestBody(
  expoScope: ExpoScope.fromJson(json['expo_scope'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerKdsExpoScopeRequestBodyToJson(
  V1OwnerKdsExpoScopeRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'expo_scope': instance.expoScope,
};
