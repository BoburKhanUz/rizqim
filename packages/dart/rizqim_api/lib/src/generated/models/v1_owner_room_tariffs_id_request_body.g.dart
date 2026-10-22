// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_room_tariffs_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerRoomTariffsIdRequestBody _$V1OwnerRoomTariffsIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerRoomTariffsIdRequestBody(
  branchId: json['branch_id'] as String?,
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  hallId: (json['hall_id'] as num?)?.toInt(),
  tableKind: json['table_kind'] == null
      ? null
      : TableKind.fromJson(json['table_kind'] as String?),
  dayType: json['day_type'] == null
      ? null
      : DayType.fromJson(json['day_type'] as String),
  price: json['price'] as num?,
  validFrom: json['valid_from'] == null
      ? null
      : DateTime.parse(json['valid_from'] as String),
  validTo: json['valid_to'] == null
      ? null
      : DateTime.parse(json['valid_to'] as String),
);

Map<String, dynamic> _$V1OwnerRoomTariffsIdRequestBodyToJson(
  V1OwnerRoomTariffsIdRequestBody instance,
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
