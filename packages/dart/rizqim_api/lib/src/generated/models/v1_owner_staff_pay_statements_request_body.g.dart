// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_statements_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPayStatementsRequestBody
_$V1OwnerStaffPayStatementsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerStaffPayStatementsRequestBody(
      branchId: (json['branch_id'] as num?)?.toInt(),
      date: json['date'] as String?,
    );

Map<String, dynamic> _$V1OwnerStaffPayStatementsRequestBodyToJson(
  V1OwnerStaffPayStatementsRequestBody instance,
) => <String, dynamic>{'branch_id': instance.branchId, 'date': instance.date};
