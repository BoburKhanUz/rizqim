// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data178.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data178 _$Data178FromJson(Map<String, dynamic> json) => Data178(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data178ToJson(Data178 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
