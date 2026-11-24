// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data35.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data35 _$Data35FromJson(Map<String, dynamic> json) => Data35(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data35ToJson(Data35 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
