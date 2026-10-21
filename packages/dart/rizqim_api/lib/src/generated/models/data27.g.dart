// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data27 _$Data27FromJson(Map<String, dynamic> json) => Data27(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data27ToJson(Data27 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
