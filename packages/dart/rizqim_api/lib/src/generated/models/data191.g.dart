// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data191.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data191 _$Data191FromJson(Map<String, dynamic> json) => Data191(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  diningTableName: json['dining_table_name'] as String?,
  hallId: (json['hall_id'] as num?)?.toInt(),
  tableKind: json['table_kind'] as String?,
  dayType: json['day_type'] as String,
  price: json['price'] as String,
  validFrom: json['valid_from'] as String?,
  validTo: json['valid_to'] as String?,
);

Map<String, dynamic> _$Data191ToJson(Data191 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'dining_table_id': instance.diningTableId,
  'dining_table_name': instance.diningTableName,
  'hall_id': instance.hallId,
  'table_kind': instance.tableKind,
  'day_type': instance.dayType,
  'price': instance.price,
  'valid_from': instance.validFrom,
  'valid_to': instance.validTo,
};
