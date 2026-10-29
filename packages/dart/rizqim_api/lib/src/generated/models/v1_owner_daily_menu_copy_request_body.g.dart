// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_daily_menu_copy_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDailyMenuCopyRequestBody _$V1OwnerDailyMenuCopyRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerDailyMenuCopyRequestBody(
  branchId: (json['branch_id'] as num?)?.toInt(),
  date: json['date'] == null ? null : DateTime.parse(json['date'] as String),
  fromDate: json['from_date'] == null
      ? null
      : DateTime.parse(json['from_date'] as String),
);

Map<String, dynamic> _$V1OwnerDailyMenuCopyRequestBodyToJson(
  V1OwnerDailyMenuCopyRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date?.toIso8601String(),
  'from_date': instance.fromDate?.toIso8601String(),
};
