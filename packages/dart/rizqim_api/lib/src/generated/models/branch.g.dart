// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Branch _$BranchFromJson(Map<String, dynamic> json) => Branch(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  distanceKm: json['distance_km'] as num,
);

Map<String, dynamic> _$BranchToJson(Branch instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'distance_km': instance.distanceKm,
};
