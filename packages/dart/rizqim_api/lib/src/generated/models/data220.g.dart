// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data220.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data220 _$Data220FromJson(Map<String, dynamic> json) => Data220(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data220ToJson(Data220 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
