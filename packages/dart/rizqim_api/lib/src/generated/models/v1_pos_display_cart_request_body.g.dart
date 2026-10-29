// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_display_cart_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosDisplayCartRequestBody _$V1PosDisplayCartRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosDisplayCartRequestBody(
  state: State.fromJson(json['state'] as String),
  seq: (json['seq'] as num).toInt(),
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines24.fromJson(e as Map<String, dynamic>))
      .toList(),
  subtotal: json['subtotal'] as String,
  total: json['total'] as String,
  session: json['session'] as String?,
  discount: json['discount'] as String?,
  queueNumber: (json['queue_number'] as num?)?.toInt(),
  payment: json['payment'] == null
      ? null
      : Payment4.fromJson(json['payment'] as Map<String, dynamic>),
);

Map<String, dynamic> _$V1PosDisplayCartRequestBodyToJson(
  V1PosDisplayCartRequestBody instance,
) => <String, dynamic>{
  'state': instance.state,
  'seq': instance.seq,
  'session': instance.session,
  'lines': instance.lines,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'total': instance.total,
  'queue_number': instance.queueNumber,
  'payment': instance.payment,
};
