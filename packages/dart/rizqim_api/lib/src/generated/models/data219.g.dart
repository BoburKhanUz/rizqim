// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data219.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data219 _$Data219FromJson(Map<String, dynamic> json) => Data219(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data219ToJson(Data219 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
