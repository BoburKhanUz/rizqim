// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_dining_tables_layout_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDiningTablesLayoutRequestBody
_$V1OwnerDiningTablesLayoutRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerDiningTablesLayoutRequestBody(
      branchId: (json['branch_id'] as num).toInt(),
      tables: (json['tables'] as List<dynamic>)
          .map((e) => Tables.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$V1OwnerDiningTablesLayoutRequestBodyToJson(
  V1OwnerDiningTablesLayoutRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'tables': instance.tables,
};
