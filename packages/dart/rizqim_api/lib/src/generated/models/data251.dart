// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data251.g.dart';

@JsonSerializable()
class Data251 {
  const Data251({
    required this.requestId,
    required this.printerId,
    required this.targetDevice,
  });

  factory Data251.fromJson(Map<String, Object?> json) =>
      _$Data251FromJson(json);

  @JsonKey(name: 'request_id')
  final String requestId;
  @JsonKey(name: 'printer_id')
  final int? printerId;
  @JsonKey(name: 'target_device')
  final String? targetDevice;

  Map<String, Object?> toJson() => _$Data251ToJson(this);
}
