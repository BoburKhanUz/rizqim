// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cart.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cart _$CartFromJson(Map<String, dynamic> json) => Cart(
  terminal: json['terminal'] as String,
  seq: (json['seq'] as num).toInt(),
  session: json['session'] as String?,
  state: json['state'] as String,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines25.fromJson(e as Map<String, dynamic>))
      .toList(),
  subtotal: json['subtotal'] as String,
  discount: json['discount'] as String,
  total: json['total'] as String,
  queueNumber: (json['queue_number'] as num?)?.toInt(),
  payment: json['payment'] == null
      ? null
      : Payment5.fromJson(json['payment'] as Map<String, dynamic>),
  updatedAt: json['updated_at'] as String,
);

Map<String, dynamic> _$CartToJson(Cart instance) => <String, dynamic>{
  'terminal': instance.terminal,
  'seq': instance.seq,
  'session': instance.session,
  'state': instance.state,
  'lines': instance.lines,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'total': instance.total,
  'queue_number': instance.queueNumber,
  'payment': instance.payment,
  'updated_at': instance.updatedAt,
};
