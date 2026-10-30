// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data85.dart';

part 'get_v1_owner_fiscal_terminal_flow_response.g.dart';

@JsonSerializable()
class GetV1OwnerFiscalTerminalFlowResponse {
  const GetV1OwnerFiscalTerminalFlowResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFiscalTerminalFlowResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerFiscalTerminalFlowResponseFromJson(json);

  final bool success;
  final String message;
  final Data85 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerFiscalTerminalFlowResponseToJson(this);
}
