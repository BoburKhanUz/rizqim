// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'lines15.dart';
import 'payment5.dart';

part 'cart.g.dart';

@JsonSerializable()
class Cart {
  const Cart({
    required this.terminal,
    required this.seq,
    required this.session,
    required this.state,
    required this.lines,
    required this.subtotal,
    required this.discount,
    required this.total,
    required this.queueNumber,
    required this.payment,
    required this.updatedAt,
  });

  factory Cart.fromJson(Map<String, Object?> json) => _$CartFromJson(json);

  final String terminal;
  final int seq;
  final String? session;
  final String state;
  final List<Lines15> lines;
  final String subtotal;
  final String discount;
  final String total;
  @JsonKey(name: 'queue_number')
  final int? queueNumber;
  final Payment5? payment;
  @JsonKey(name: 'updated_at')
  final String updatedAt;

  Map<String, Object?> toJson() => _$CartToJson(this);
}
