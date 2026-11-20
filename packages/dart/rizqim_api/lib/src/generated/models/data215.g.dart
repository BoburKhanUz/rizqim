// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data215.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data215 _$Data215FromJson(Map<String, dynamic> json) => Data215(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data215ToJson(Data215 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
