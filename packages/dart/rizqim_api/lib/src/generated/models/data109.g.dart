// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data109.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data109 _$Data109FromJson(Map<String, dynamic> json) => Data109(
  branchId: (json['branch_id'] as num?)?.toInt(),
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data109ToJson(Data109 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'ingredients': instance.ingredients,
};
