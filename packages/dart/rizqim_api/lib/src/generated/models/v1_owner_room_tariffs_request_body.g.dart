// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_room_tariffs_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerRoomTariffsRequestBody _$V1OwnerRoomTariffsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerRoomTariffsRequestBody(
  branchId: (json['branch_id'] as num).toInt(),
  dayType: DayType.fromJson(json['day_type'] as String),
  price: json['price'] as num,
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  hallId: (json['hall_id'] as num?)?.toInt(),
  tableKind: json['table_kind'] == null
      ? null
      : TableKind.fromJson(json['table_kind'] as String?),
  validFrom: json['valid_from'] == null
      ? null
      : DateTime.parse(json['valid_from'] as String),
  validTo: json['valid_to'] == null
      ? null
      : DateTime.parse(json['valid_to'] as String),
);

Map<String, dynamic> _$V1OwnerRoomTariffsRequestBodyToJson(
  V1OwnerRoomTariffsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'dining_table_id': instance.diningTableId,
  'hall_id': instance.hallId,
  'table_kind': instance.tableKind,
  'day_type': instance.dayType,
  'price': instance.price,
  'valid_from': instance.validFrom?.toIso8601String(),
  'valid_to': instance.validTo?.toIso8601String(),
};
