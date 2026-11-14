// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill2.dart';
import 'order4.dart';
import 'billing.dart';

part 'requests.g.dart';

@JsonSerializable()
class Requests {
  const Requests({
    required this.requestId,
    required this.reprint,
    required this.printerId,
    required this.targetDevice,
    required this.requestedBy,
    required this.requestedAt,
    required this.bill,
    required this.order,
    required this.billing,
  });

  factory Requests.fromJson(Map<String, Object?> json) =>
      _$RequestsFromJson(json);

  @JsonKey(name: 'request_id')
  final String requestId;
  final bool reprint;
  @JsonKey(name: 'printer_id')
  final int? printerId;
  @JsonKey(name: 'target_device')
  final String? targetDevice;
  @JsonKey(name: 'requested_by')
  final int? requestedBy;
  @JsonKey(name: 'requested_at')
  final String? requestedAt;
  final Bill2 bill;
  final Order4 order;
  final Billing billing;

  Map<String, Object?> toJson() => _$RequestsToJson(this);
}
