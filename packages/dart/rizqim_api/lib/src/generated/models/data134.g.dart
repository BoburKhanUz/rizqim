// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data134.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data134 _$Data134FromJson(Map<String, dynamic> json) => Data134(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data134ToJson(Data134 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
