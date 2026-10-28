// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data115.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data115 _$Data115FromJson(Map<String, dynamic> json) => Data115(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data115ToJson(Data115 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
