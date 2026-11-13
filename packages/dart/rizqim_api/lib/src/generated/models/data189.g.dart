// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data189.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data189 _$Data189FromJson(Map<String, dynamic> json) => Data189(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data189ToJson(Data189 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
