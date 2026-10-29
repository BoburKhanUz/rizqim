// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'state.dart';
import 'lines14.dart';
import 'payment4.dart';

part 'v1_pos_display_cart_request_body.g.dart';

@JsonSerializable()
class V1PosDisplayCartRequestBody {
  const V1PosDisplayCartRequestBody({
    required this.state,
    required this.seq,
    required this.lines,
    required this.subtotal,
    required this.total,
    this.session,
    this.discount,
    this.queueNumber,
    this.payment,
  });

  factory V1PosDisplayCartRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosDisplayCartRequestBodyFromJson(json);

  final State state;
  final int seq;
  final String? session;
  final List<Lines14> lines;
  final String subtotal;
  final String? discount;
  final String total;
  @JsonKey(name: 'queue_number')
  final int? queueNumber;
  final Payment4? payment;

  Map<String, Object?> toJson() => _$V1PosDisplayCartRequestBodyToJson(this);
}
